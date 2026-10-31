---
name: erp-translation-traps
description: Short translation keys that read wrong as ERP labels (Quote, By, From, To, Count, Record, Due, Net, Method) and the longer keys ERP screens use instead.
---

## Which existing keys read wrong on an ERP screen?
- The English source is the key (docs/memory/core/translation-rules.md), and some short keys already carry a Turkish meant for another context: `'Quote'` → "Alıntı" (a citation), `'By'` → "Şuna Göre", `'From'` / `'To'` → "Şundan" / "Şuna", `'Count'` → "Adet", `'Record'` → "Kayıt", `'Due'` → "Bitiş", `'Net'` → "KDV Hariç", `'Method'` → "Metod", `'Collection'` → "Koleksiyon" (the designer's collections).
- ERP text uses a key of its own instead: `'Sales quote'` (Satış teklifi; `'Offer'` is "Teklif"), `'Quote No'`, `'Valid until'`, `'Sent by'`, `'Start date'`, `'Start'` / `'End'`, `'Net change'`, `'Save'`, `'Overdue'`, `'Due date'`, `'Receipt'` (Tahsilat), `'Payment method'`.
- The accountant pack's Receipts and payments sheet and the receipts CSV called a receipt "Koleksiyon" and the method column "Metod"; they now use `'Receipt'` and `'Payment method'`, as the panel does (#4001, PR #4184).
- Before writing a new key, look up its translation in its shard (`includes/local/tr/<first letter>.json`); `php tools/check_lang.php` only says whether a key exists, not whether it reads right.
- The cash flow screen's own case: erp-cashflow-report.md.
