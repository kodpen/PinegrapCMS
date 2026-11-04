---
name: feature-roadmap-2026-10
description: Feature roadmap decided 2026-10-19 from the gap analysis: 24 approved plans in four groups, wave order, the legacy rule, one task per session; index of the plan files
---

## What was decided on 2026-10-19?
- All 24 candidates of the October 2026 gap analysis are approved as plans (one file each, listed below). Research evidence: `docs/_ozellik_adaylari.md`.
- Legacy code is never removed by these plans: old payment gateways, the classic checkout flow, classic styles and renderers stay as they are and keep working. New work is added beside them (frozen, not deleted). Retiring the legacy page designer is a separate decision (`designer/page-designer-retired.md`).
- Open questions of the analysis were closed with the recommended answers.

## What is the wave order?
- Done: `checkout-legal-documents`, `price-history-reference-price`, `ops-alerts-safe-updates`, `seo-toolkit-redirects`, `ai-write-buttons`, `agent-product-feeds`, `docker-image`, `sms-and-iys`, `customer-returns-flow`, `image-delivery-pipeline`, `page-revisions-and-scheduling`, `storefront-conversion-pack`, `accessibility-and-consent-tools`, `passkeys`, `payment-driver-layer`, `privacy-data-pack-audit-log`, `subscriptions-iyzico-stripe`, `designer-interactions-collections`.
1. Now, one small PR series each, no further decision needed: `erp-auto-invoice-earchive`.
3. Long-running, 10+ PRs, design decision first: `marketplace-trendyol-hepsiburada` (Trendyol, then Hepsiburada), `shipping-carrier-labels` (after `shipping-management`).
4. Watch, re-check each quarter: `storefront-api-agentic-commerce`, `addon-hook-system` (owner 2026-10-30: after 2026.11.0, #3344), `visitor-ai-assistant` (owner 2026-10-30: stays, #3313).

## Who takes what?
- Each plan step is one PR claimed with a draft PR (CLAUDE.md, Claim work; `workflow/session-lifecycle.md`). Areas follow the memory-folder table in CLAUDE.md.

## Which rules bind every plan?
- Owner rules (`workflow/owner-rules.md`), CLAUDE.md fixed rules, `workflow/decisions-made.md`. Schema only through 2026.11.0 migration steps. Express-order totals never change (`get_express_order.php`, `get_order_preview.php`). A plan-driven feature gets one `[NEW]` changelog line when it ships; plans get none.
- When a plan is finished its lasting decisions move to the topic folder and the plan file is deleted.
