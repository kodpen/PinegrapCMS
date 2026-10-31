---
name: panel-shared-keys
description: Short keys shared by many screens that were read in the wrong sense (SEO, Created, Given, the uptime note, Quotes, reviews, Edit/Restore as a source) and the separate keys used instead
---

## Which shared keys were wrong, and how were they fixed (#3608)?
- `SEO` read "Ar.Mot.Opt."; it is "SEO" in Turkish too. Value only: every SEO label uses the key.
- `Given` is "Verildi", a state: the cheque status (`includes/erp/cheques.php`) and the privacy export's consent (`privacy_export.php`). The cheque direction on `erp_cheques.php` (row label and filter, beside `Received` "Alınan") uses its own key `Given out` = "Verilen".
- `Created` is "Oluşturulma", a column header used by about 30 lists. The audit trail's `create` action label (`pg_audit_view_labels()`, `includes/fn/audit_view.php`) uses its own key `Record created` = "Oluşturuldu" (#3712).
- The uptime note on the general settings ("Uptime monitors can poll this address…") read "Erişilebilirlik izleyicileri", the a11y report's word. It is "Çalışma süresi (uptime) izleyicileri …", colon kept.

- The flow screens (`fl_*` in `includes/workspace/flows.php`) read `State` "Eyalet/İl", `Last run` "Son koşum", `Redo` "İleri al", `People` "İnsanlar", `Join` "Katıl" and more. They use `Flow state`, `Last flow run`, `Event start`, `Join start`, `Approval given`, `Approval expired`, `Wait hours`, `Wait days`, `People to share with`, `Redo change`, and `Done` for the run flag (#3754); pinned in `tests/workspace_flows_labels_test.php`.
- The comments quote starter (`includes/fn/widgets_starters.php`) read `Quotes` "Fiyat Teklifleri" (the ERP screen); it uses `Quotations` = "Alıntılar". The product rating line read `reviews` "degerlendirmeler", the design templates' slug key; it uses `review(s)` = "değerlendirme". Slug keys are for addresses only (#4204).
- A collection item revision's source (`pg_record_revision_source_label()`, `includes/fn/record_revisions.php`) read the button keys `Edit` / `Restore`; it uses the nouns `Edited` = "Düzenleme" and `Restoration` = "Geri yükleme" (`Restored` is "Geri yüklendi", a state used elsewhere) (#4204).

## What is the rule?
- A short key keeps the sense most screens use; a screen that needs another sense gets a longer key ([[translation-rules]]). Values and the source are pinned in `tests/panel_shared_keys_test.php`.
