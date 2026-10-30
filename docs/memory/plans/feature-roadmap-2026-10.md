---
name: feature-roadmap-2026-10
description: Feature roadmap decided 2026-10-19 from the gap analysis: 24 approved plans in four groups, wave order, the legacy rule, one task per session; index of the plan files
---

## What was decided on 2026-10-19?
- All 24 candidates of the October 2026 gap analysis are approved as plans (one file each, listed below). Research evidence: `docs/_ozellik_adaylari.md`.
- Legacy code is never removed by these plans: old payment gateways, the classic checkout flow, classic styles and renderers stay as they are and keep working. New work is added beside them (frozen, not deleted). Retiring the legacy page designer is a separate decision (`legacy-page-designer-retirement.md`).
- Page revision history **and** scheduled publishing are approved; live pages still publish directly (no working copy).
- Open questions of the analysis were closed with the recommended answers; each plan file records its own.

## What is the wave order?
1. Now, one small PR series each, no further decision needed: `checkout-legal-documents` (done 2026-10-28: `commerce/order-legal-documents.md`, `checkout-kvkk-notice.md`), `price-history-reference-price` (done 2026-10-27: `commerce/product-reference-price.md`, `product-reference-price-storefront.md`, `product-reference-price-decisions.md`), `erp-auto-invoice-earchive`, `ops-alerts-safe-updates` (done 2026-10-19: `platform/ops-alerts.md`, `update-backup-rollback.md`, `maintenance-mode-health.md`); when capacity allows `seo-toolkit-redirects` (done 2026-10-23: `platform/short-link-redirects.md`, `page-rename-redirect.md`, `not-found-log.md`, `api/api-seo-not-found.md`), `ai-write-buttons` (done 2026-10-29: `ui/ai-write.md`, `ai-write-decisions.md`), `agent-product-feeds` (done 2026-10-19: `commerce/product-feeds.md`, `product-feed-serving.md`, `product-feed-decisions.md`), `docker-image` (done 2026-10-20: `platform/docker.md`, `docker-decisions.md`, `docker-image-publishing.md`). In parallel the first slice of `sms-and-iys`.
2. Mid-term, 3–6 PRs each: `sms-and-iys`, `customer-returns-flow` (done 2026-10-25: `commerce/order-returns-scope.md`), `image-delivery-pipeline` (done 2026-10-20: `platform/image-derivatives.md`, `image-delivery-settings.md`, `designer/responsive-images.md`, `image-loading.md`; prefetch: `platform/speculation-prefetch.md`), `page-revisions-and-scheduling` (done 2026-10-20: `designer/page-revisions.md`, `page-schedule.md`, `page-revisions-schedule-api.md`), `storefront-conversion-pack` (done 2026-10-28: `commerce/storefront-pack-decisions.md`), `accessibility-and-consent-tools` (done 2026-10-26: `platform/accessibility-compliance.md`, `accessibility-report.md`), `passkeys`.
3. Long-running, 10+ PRs, design decision first: `marketplace-trendyol-hepsiburada` (Trendyol, then Hepsiburada), `shipping-carrier-labels` (after `shipping-management`), `payment-driver-layer` (done); `privacy-data-pack-audit-log` is done (core/personal-data-export.md, core/personal-data-erasure.md).
4. Watch, re-check each quarter: `storefront-api-agentic-commerce`, `addon-hook-system` (owner 2026-10-30: after 2026.11.0, #3344), `visitor-ai-assistant` (owner 2026-10-30: stays, #3313), `subscriptions-iyzico-stripe` (started #3183), `designer-interactions-collections`.

## Who takes what?
- Each plan step is one PR, claimed with a draft PR (CLAUDE.md, Claim work); its session takes the plan's next step after the merge (workflow/session-lifecycle.md). Areas follow the memory-folder table in CLAUDE.md.

## Which rules bind every plan?
- Owner rules (`workflow/owner-rules.md`), CLAUDE.md fixed rules, `workflow/decisions-made.md`. Schema only through 2026.11.0 migration steps. Express-order totals (`get_express_order.php`, `get_order_preview.php`) never change. A plan-driven feature gets one `[NEW]` changelog line when it ships; plans get none.
- When a plan is finished its lasting decisions move to the topic folder and the plan file is deleted.
