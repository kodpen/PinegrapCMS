---
name: _plan_erp_ozellikler
description: ERP features plan, phases 6-9 (done); remaining manual checks on dev
---

# Plan — ERP özellikleri: Faz 6–9

**Durum (2026-10-14):** Faz 6, 7, 8 (8a, 8b, 8c) ve 9 bitti (PR #270–#319, API parçası
#329, `.ods` ekstresi #330; ayrıntı git geçmişindeki `docs/degisiklikler.md`). Biten fazların bölümleri
çıkarıldı; tam metin: `git show d9fec3a:docs/_plan_erp_ozellikler.md`. Önceki plan `docs/_plan_erp.md`.

## Kalanlar

Erdal'ın dev.pinegrap.com'da yapacağı üç elle doğrulama (§8). §7 bu planın kapsamında
değil; Erdal'ın yerel e-Belge işi için not, oradaki açık işler `docs/_plan_erp.md`
Kalanlar'da da var.

## 7. e-Belge sürücü arayüzü (Erdal'ın yerel işi için kısa not)

Bulut thread'leri bu bölümdeki dosyalara dokunmaz.

- Sözleşmenin tek doğru kaynağı `includes/erp/edoc/registry.php` başlık yorumu. Yeni
  sağlayıcı = `includes/erp/edoc/<kod>.php` + `erp_edoc_drivers()`'a bir satır; kod bir
  kez belge gönderilince değişmez.
- Fonksiyonlar `erp_edoc_<kod>_`: `info` (yetenekler: `einvoice`, `earchive`, `ewaybill`,
  `inbox`, `accounts`, `return`), `fields`, `ping`, `check_taxpayer`, `send_invoice`,
  `poll`, `cancellable`, `cancel_invoice`, `fetch_document`, `send_waybill`,
  `date_refusal`, `inbox`, `inbox_document`, `accounts`, `account`, `account_save`.
  Tanımlanmayan işlem desteklenmiyor sayılır.
- HTTP yalnız `erp_edoc_http()`, günlük `erp_edoc_log()` (maskeli); kimlikler
  `erp_edoc_providers`'da şifreli; oturum `erp_edoc_session()` / `_save()`.
- Gönderimden önce reddedilecek her kural `party_missing` / `document_missing` listesine
  (`erp_edoc_tax_number_valid()`, `erp_edoc_postcode_valid()`, `erp_edoc_person_name()`).
  Kalıp: `includes/erp/edoc/isbasi.php`.
- Aynı alana düşen açık işler: `erp_edoc_queue` tablosu var, işleyicisi yok (#2); GİB
  UBL-TR kod listeleri v1.43 14 Eylül 2026'da yürürlüğe girdi, istisna ve tevkifat kodları
  gözden geçirilmeli (#3). Sağlayıcı karşılaştırması proje notunda
  (`notes/efatura-saglayici-karsilastirma.md`).

## 8. Elle doğrulanacaklar (Erdal)

- dev.pinegrap.com'da Claude bağlayıcısıyla Faz 6 araçları (yeniden bağlanarak).
- Satın alma siparişi PDF'i ve tedarikçi e-postası (dev'de e-posta gitmez; ekran ve ek).
- Bir şablonda Hesabım → Faturalarım görünümü; cari fiyatlı müşteriyle sepet.
