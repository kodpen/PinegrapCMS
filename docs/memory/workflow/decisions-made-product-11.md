---
name: decisions-made-product-11
description: Decisions list, product part 11 (2026-10-30 night on): CRM shape, storefront API timing, the two support e-mail fields, slash commands over the API, e-mail-a-friend limit off the log
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## What shape does a contact pipeline take? (#3797)
- Decided (architect, 2026-10-30, CRM seams question 1): nothing more in 2026.11.0; `plans/workspace-crm-seams.md` closes when the 10.1 field (#3796) and the 10.3 param land. Afterwards (a): a pipeline is a workflow table with a status column (stages) and a record-link column of kind `contact`; custom fields are columns of that table; the customer stays the contact. To build then: a "Sales pipeline" starter template and a customer-card section listing the rows that link the contact; no schema. No own CRM module: tables, board, merge and erasure exist. Re-ask if per-table fields or the 5000-row limit bind.

## When does the storefront API start? (#3798)
- Decided (architect, 2026-10-30): the design note now, a memory-only PR under `plans/` (route table, token model, error vocabulary, the `auth_tokens.kind` proposal, ACP/UCP mapping), reviewed by the architect; the coding slices (auth, cart, checkout, customer, ACP, UCP manifest, WAF agent verification) after 2026.11.0 ships, like the add-on system (#3344). The customer-token migration step is approved when the auth slice starts. Running sites: nothing changes.

## Two "Support E-mail Address" fields? (#3799)
- Decided (architect, 2026-10-30, settings recategorization R4): two fields, two names. General › Organization keeps "Support E-mail Address" (`config.email_address`); the Affiliate Program card keeps `affiliate_email_address`, relabelled "Affiliate Program E-mail Address" (`tools/tr_add.php`, "Ortaklık Programı E-posta Adresi"). No column, save or reader change. Refused: one field (where the two addresses differ, who receives the affiliate notices would change); both in Organization (a setting lives with its feature).

## Do slash commands run over the API? (#3801)
- Decided (architect, 2026-10-30): yes, `POST /workspace/commands/run` (`workspace.commands.run`, `workspace:write`, `'mcp' => false` like the list route): `channel_id`, `text` and the composer's options (`confirm`, `force`, `assignees`, `from`, `decision`, `on_sale`, `parent_id`); `dry_run` by the `X-Dry-Run` rule returns the preview without a write; `ws_claude_command_body()` / `ws_ai_command_body()` first, as the composer; text that is no command is 400 `not_a_command`. Rights re-checked inside `ws_command_run()` as in the chat. The contract is "what the web composer accepts", said in the OpenAPI description; an integration wanting a stable contract uses the typed endpoints. Reason: a chat client on the team-devices API must run the same `/` commands as the web composer. Plan step 7, one PR, no schema.
- Refused: record-card commands (`/sipariş`…; the `#` picker and the ref drawer show records) and Enter saving a /price or /stock card (live product data takes a deliberate Save).

## E-mail-a-friend daily limit off the Site Log text? (#3802)
- Decided (architect, 2026-10-30): no schema; the per-address limit (10 a day) moves to the WAF rate store like the custom form's `cfr`: scope `eaf`, window 86400 s in `waf_rate_long_scopes()`, `waf_rate_hits()` checked before sending, `waf_rate_record()` after the e-mail went; editors of the folder exempt as before. The log line is written through `pg_log_lang()`. Accepted: the store's fixed day window, fail-open without `waf_rate`, a fresh count after the upgrade. Refused: a `log.log_kind` column or a counter table (slow ALTER on old sites' large `log`, backfill), matching the translated phrase (language-dependent). After #3780 lands (`waf.php`).
