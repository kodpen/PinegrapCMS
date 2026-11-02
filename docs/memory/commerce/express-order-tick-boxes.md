---
name: express-order-tick-boxes
description: When the express order widget fills its billing tick boxes (opt_in, opt_in_sms, tax_exempt, update_contact), and the refused-submit fault it fixed.
module: widgets_express_order
---

## When are the billing tick boxes filled?
- Only before the visitor's first submit (`$lf->prefill()`), in `_eo_prefill_billing_boxes()`: opt_in and tax_exempt from the order, opt_in_sms ticked, update_contact ticked unless `$_SESSION['software']['update_contact']` is false. The classic express order page fills them only while `billing_first_name` is not in the liveform, the same moment.
- After a submit the boxes show what was posted. An unticked box posts nothing, so `field_in_session()` cannot tell "unticked" from "never shown"; a per-field gate re-fills it.

## What did it fix?
- Before #5082 each box was gated by `field_in_session()`: after a refused submit (a declined card, a missing field) an unticked SMS or e-mail consent box came back ticked, so a retry recorded consent the visitor had refused. Test: `tests/express_order_prefill_boxes_test.php`.
- Text fields keep their per-field gate: a posted empty text field is in the session.
