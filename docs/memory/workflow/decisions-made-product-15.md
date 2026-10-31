---
name: decisions-made-product-15
description: Decisions list, product part 15 (2026-10-31 afternoon on): cancelled order's affiliate commission voided; image editor redacted save keeps versions, original plus 10 saves, site button, SVG animation
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; its Parts line lists the files. Entries name the issue and the date.

## What happens to an affiliate commission when its order is cancelled? (#4174)
- Decided (architect, 2026-10-31): voided with the order. `process_order_cancellation()` sets the order's `pending`/`payable` commissions to `ineligible`, keeping the old status in `commissions.status_before_cancel`, and stops its enabled recurring profiles (`disabled_by_cancel = 1`); `pg_order_restore_cancelled()` puts both back where the marker is still set and the row is still `ineligible`/disabled; a hand edit clears the marker. A `paid` commission stays. The Commissions screen marks a cancelled order's commission with a badge read from `orders.status` (older cancellations too); totals stay by status. No backfill; a partial refund moves nothing; the four statuses stay (no `cancelled` status: an enum MODIFY and an API schema change). Reason: the cancellation's design already leaves no spendable gift card behind; a commission for a sale that did not happen is the same fault. Refused: a flag alone (still payable by mistake) and leaving it to the operator.

## Image editor: redacted save, retention, the site's edit button, SVG animation (#4203)
- Owner, 2026-10-31. A redacted save replaces the picture in place and strips the served file's metadata (an EXIF thumbnail can hold the unredacted picture); the earlier versions, the original included, stay under the normal retention: the redaction is one more save, reversible by whoever may open the editor on that folder (`check_edit_access()`), and versions are read only through the editor API. Refused: deleting the unredacted versions, a redacted save as a copy.
- Retention: the original plus the last 10 saves per file, no setting; deleting the file deletes them; their bytes count in the File Manager's Disk Usage.
- The live site's edit button opens the editor modal on the page; its bundle loads only for a signed-in editor, nothing for visitors. Refused: a jump to the File Manager, removing the button.
- E19 stays: a few CSS keyframe presets per layer (fade, slide, pulse, spin) written into the saved SVG, no script; the plan's last step.
