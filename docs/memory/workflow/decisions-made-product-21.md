---
name: decisions-made-product-21
description: Decisions list, product part 21 (2026-11-03 on): clearing the performance log, the Migration screen, panel transitions, the panel back/forward cache
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Clearing the performance log follows the activity log's rule (2026-11-03, #5843)
- Owner (2026-11-02 night) delegated the question to the architect; decided: the "Clear all performance log data" button and the `submit_clear` branch of `view_performance_log.php` follow `MASS_DELETION == true || USER_ROLE < 1`, as the activity log's delete controls do; a role outside it sees no button and the server refuses. Reason: one rule for clearing logs; opening the screen (`manager`) and wiping diagnostics for good are separate rights.

## The Migration screen carries classic content only (2026-11-03, #6006)
- Owner (2026-11-02 night) delegated the question to the architect; decided: `migration.php` copies the classic columns of folders, pages, styles, common and designer regions; its heading and help say so, it warns before copying when the source reports pages with a page tree, and the source's Recycle Bin, Drafts and Workspace folders are skipped. Carrying designer trees with remapped ids is a plan of its own, opened only when the owner orders it. Reason: designer pages arrived empty without a word; the full carry is large.
- Owner (2026-11-03): carrying designer content joins the next plan, `plans/legacy-to-designer.md` (LD4); the classic-only screen with its warning stays until then.

## The dashboard has no Subscriptions card (2026-11-03)
- Owner: the `subs` card (licence, renewal date, period bar, support package) is not built and has no plan; it may be looked at again later. N12c leaves the dashboard plan.

## Panel transitions are light (2026-11-03, #6080)
- Owner: no exit curtain; a thin top bar and `cursor: progress` after 150 ms, the old page visible, held until `pagehide`; no timer or interaction exits, a cancelled `beforeunload` / `confirm()` shows nothing. On arrival the curtain is transparent for ~120 ms and fades in ~100 ms; `pgPreloaderHold` screens keep it. The logo leaves everyday transitions; `PRIVATE_LABEL` unchanged. Reason: most screens arrive in 0.2-0.45 s and the curtain covered even a fast one ~330 ms.

## Panel pages may enter the back/forward cache (2026-11-03, #6081)
- Owner: panel HTML is sent `private, no-cache` (no `no-store`) from one place; the sign-out answer carries `Clear-Site-Data: "cache"`; a page restored on `pageshow` asks for the session and goes to sign-in when it is gone; secret screens (API keys, recovery codes, password reset, 2FA setup, connected-account tokens) keep `no-store`; in-page JSON replies drop `no-store` too. Reason: list, record, Back is the panel's most frequent move.
