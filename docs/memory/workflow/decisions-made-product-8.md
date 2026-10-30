---
name: decisions-made-product-8
description: Decisions list, product part 8 (2026-10-30 midday on): Trendyol-carried packages refused by Create Label, starter dump corrections for #3330, marketing SMS in 2026.11.0, forum template wording
---

## Does Create Label refuse a Trendyol-carried package the common label does not cover? (#3568)
- Decided (architect, 2026-10-30, marketplace plan): yes. `mp_label_ship_to()` gains a third answer, "carried by the marketplace" (an order from a marketplace account that does not ship itself whose package is on Trendyol's carrier but outside the common label: another carrier or a stage no longer open); the pure `pg_shipment_label_create_refusal()` answers it with the code `marketplace_carried` beside `marketplace_label`, naming Trendyol's carrier; order screen, bulk summary and API show the same refusal. Accounts that ship themselves are untouched. Reason: a second label is a paid shipment nobody uses. No schema.

## Are the starter dumps corrected for #3330? (#3567)
- Decided (architect, 2026-10-30, an exception to `decisions-made-data.md`): the dead AddThis code is emptied in both dumps, the Turkish dump's `config.title` becomes `Web Sitem` and the `AT-ONCE` arrival name `En kısa sürede`; one idempotent 2026.11.0 data step (no schema) empties `config.social_networking_code` only where it still equals one of the two stock AddThis scripts byte for byte; the title and the arrival name are never migrated, since an owner may have written them.

## Is marketing SMS built in 2026.11.0? (#3570)
- Decided (architect, 2026-10-30, under the owner's position that marketing is a feature and İYS never narrows it): yes, plan `sms-and-iys.md` steps 7-10. The SMS consent box sits beside the e-mail marketing box in the same places and starts as it does (#2431), `consent_log` channel `sms`, `contacts.iys_sms_status` (`decisions-made-schema-6.md`), the İYS `MESAJ` channel with the e-mail rule (#2430), the MERSİS/trade name and type label appended, and an SMS campaign beside the e-mail campaigns as the sender (a Flows action later). Running sites change nothing until the SMS switch and a campaign are on.

## Forum template wording: "Guide", the password page addresses, untranslated text on `/en/` (#3622)
- Decided (architect, 2026-10-30): "Guide" is "Rehber" everywhere and the password page address keys change for new installs only, an installed site keeping its pages (`core/panel-turkish-terms.md`); a translatable text drawn in the source language on a target-language page gets `lang="<source>"` on its element: `pg_tr_render_props()` marks the node, `_render_tree_node()` writes the attribute, a `lang` the user set wins, `pg_tr_render_rules()` changes so cached bodies are drawn again once. Database texts swapped in by `pg_tr_render_db_texts()` are not covered. Not opened: translating the templates' design text per site language. Refused: dropping `text-transform: uppercase` from the templates.

Related: docs/memory/workflow/decisions-made-product-7.md, docs/memory/workflow/decisions-made.md
