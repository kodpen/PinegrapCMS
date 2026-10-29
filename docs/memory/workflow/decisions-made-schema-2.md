---
name: decisions-made-schema-2
description: Decisions list, schema part 2, approved from 2026-10-28 on: e-mail, workflow tables, MCP preamble, secret widths, prompt version, discussion follow, checkout page, legal acceptance; rules in part 1
---

## Where are the rules and the earlier steps?
- `decisions-made-schema.md`: the ENUM-append rule, the steps of 2026-10-27, who decides schema. Reasons are on the issues.

## Which schema was approved on 2026-10-28?
- Incoming e-mail G1b (#2677, architect, step 11.0.82): `ws_mail_boxes`, `ws_mail_conversations` (G5's status, assignee and response-time columns included), `ws_mail_items` (`UNIQUE (box_id, rfc_message_hash)` for de-duplication); `'mail'` appended to `ws_messages.sender_kind` and `ws_message_forwards.source_sender_kind` by a guarded widening carrying every value (`workspace/mail-tables.md`).
- Workflow tables T1a (#2746, architect, step 11.0.110): `ws_wtables`, `ws_wtable_columns` (`type` VARCHAR(16)), `ws_wtable_rows` (`cells` MEDIUMTEXT JSON by column id, money in minor units, `revision`), `ws_wtable_shares` in the `ws_flow_shares` shape; limits 50/5000/500 in PHP; no MySQL JSON functions. JSON person and record-link cells need an export and erasure reader before they are filled.
- MCP agent preamble switch (#2765, architect, step 11.0.111): `ws_mcp_servers.agent_preamble TINYINT(1) NOT NULL DEFAULT 0` by `install_add_column()`, read behind `waf_table_has_column()` like `agent_tool`.
- Gateway secret columns (#2792): the four VARCHAR(100) `config` columns stay; a sealed secret fits 47 plain characters, longer new values are refused. Widening them to VARCHAR(255) by `install_modify_column()` is approved in advance should a gateway ever need it.
- AI request prompt version (#2829, architect, step 11.0.112): `ws_ai_requests.prompt_version` VARCHAR(16) NOT NULL DEFAULT '' by `install_add_column()`, read behind `waf_table_has_column()` like the table's other added columns; the value is `pg_ai_prompt_version()` (derived from the rules text), old rows stay '' (unknown); no table of rule texts, git history holds them.
- Discussion follow (#2419, architect, step 11.0.113): `ws_threads.follow_agent` VARCHAR(64) NOT NULL DEFAULT '' by `install_add_column()`, values '', `claude`, `ai`, `mcp:<server id>` (room for the agent-handle keys), read behind `pg_schema_has()` (`ws_thread_follow_ready()`); old discussions stay '' (`workspace/workspace-discussion-follow.md`).

## Which schema was approved on 2026-10-29?
- Checkout page on the order (#2984, architect, step 11.0.120): `orders.checkout_page_id INT UNSIGNED NOT NULL DEFAULT 0` by `install_add_column()`, written where the page is known (the A1 redirect UPDATE and the completing UPDATE, `pg_order_complete_columns()`), read behind `pg_schema_has()` (built in A3a, #2998: `upgrade_2026_11_0_order_checkout_page()`); the webhook completion takes the receipt settings and the auto-registration switch from that page; 0 (orders started before the step) falls back to a plain-text receipt and no account (`plans/order-completion-function.md`). General labels continue from 11.0.120 (`release/version-numbering.md`).
- Legal acceptance on the order (#3001, architect; step 11.0.121 `upgrade_2026_11_0_order_legal_acceptance()`, built in #3021): `orders.legal_acceptance TEXT NULL` by `install_add_column()`, JSON `{types, ip, user_agent, at}` written by the redirect UPDATE from the checkout's boxes, `waf_client_ip()` and the browser when the documents feature is on; the webhook completion hands them to `pg_legal_documents_store()` (optional ip, agent and time arguments; the visitor's path keeps reading its request). NULL: preinfo and contract accepted, kvkk not, IP from `orders.ip_address`, browser ''. Reason: the stored evidence and the consent-log rows are the ones the visitor gave; `orders.ip_address` holds IPv4 only.

Related: docs/memory/workflow/decisions-made-schema.md
