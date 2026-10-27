---
name: widget-default-parts-order
description: The order view's default parts (W16): timeline, status badge, the returns and legal documents cards added after it, their flags and tokens, e-mail look
---

## What do the timeline and status badge draw?
- Timeline: `ul.pg-ov-timeline` > `order.steps` list > `li.pg-ov-timeline-item`: an icon per `__step_kind` (`eq`), shipped and delivered with an `info`/`success` or, when `__step_is_estimate`, a `secondary` icon; label `__step_label`, ` — ` and `__step_note` when there is a note, date `__step_date`. Its look moved from the inline `<style>` to `pg_carousel_layout.css` (v4), loaded when the output holds `pg-ov-timeline`. `_eo_render_order_timeline()` and the `__timeline` value are gone; `has_timeline` stays.
- Status badge: six spans, `show_if __order_status_stage eq <stage>` (`pg_sw_order_status_stages()`), text `__order_status_text` ("Awaiting Payment" for an unpaid transfer), classes from `status_class_<stage>` or `_pg_order_status_badge_class()`'s defaults, baked in when the part is made. The panel's "Status Badge Classes" section is gone: the classes are edited on each badge. `__order_status_badge` has no value.
- E-mail: `pg_sw_parts_mail_styles()` writes `pg_sw_parts_mail_style_map()` inline (no flex, positioning or pseudo element; icon colour from its `bg-*`), before the element's own style.

## Which cards are added after an order view? (#1824)
- Returns first, then legal documents, as the renderer appended them. Each `div.container.pb-4` > `.card` > `.card-header` (`h6`, `lang()` heading) and `.card-body`, the classes the old blocks had.
- Returns (`pg-ov-returns-block`, flag `show_returns_card`: the setting on and a list or form): the returns list part, then the return form part ([[widget-default-parts-order-forms]]). Placed by either token or list.
- Legal documents (`pg-ov-legal-documents`, `has_legal_documents`): `ul.list-unstyled` of `li` per copy (`has_legal_preinfo`/`has_legal_contract`), each an `a` to the copy, `target _blank`, `rel noopener`; then `p` bound to `__withdrawal_text` (the whole `pg_legal_withdrawal_text()` sentence, words in the site's language) under `has_withdrawal_deadline`, left out when the tree places `__withdrawal_deadline` or `__withdrawal_text`. Placed by either link token.
- `pg_legal_documents_order_view_block()` and `pg_return_block_html()` are gone. The value stays one sentence for now: the ended line puts the date mid-sentence in Turkish; a sentence with values ([[sentence-with-values]]) can hold it in a later slice.
- An e-mail order layout also gets both cards in the editor; they never show there (no copies, no returns in the receipt).
