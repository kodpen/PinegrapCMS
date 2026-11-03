---
name: decisions-made-product-21
description: Decisions list, product part 21 (2026-11-03 on): clearing the performance log, the Migration screen carries classic content only
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue, the date and the reason.

## Clearing the performance log follows the activity log's rule (2026-11-03, #5843)
- Owner (2026-11-02 night) delegated the question to the architect; decided: the "Clear all performance log data" button and the `submit_clear` branch of `view_performance_log.php` follow `MASS_DELETION == true || USER_ROLE < 1`, as the activity log's delete controls do; a role outside it sees no button and the server refuses. Reason: one rule for clearing logs; opening the screen (`manager`) and wiping diagnostics for good are separate rights.

## The Migration screen carries classic content only (2026-11-03, #6006)
- Owner (2026-11-02 night) delegated the question to the architect; decided: `migration.php` copies the classic columns of folders, pages, styles, common and designer regions; its heading and help say so, it warns before copying when the source reports pages with a page tree, and the source's Recycle Bin, Drafts and Workspace folders are skipped. Carrying designer trees with remapped ids is a plan of its own, opened only when the owner orders it. Reason: designer pages arrived empty without a word; the full carry is large.
