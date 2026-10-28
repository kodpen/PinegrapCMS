---
name: panel-turkish-terms
description: Turkish words the panel uses for terms that were translated in several ways (Manager role, password, short link, opt-in, ERP quotes, Anonymous), and which translations were wrong
---

## Which word does the panel use?
- Role 2 "Manager": "Menajer" (TDK spelling; it was "Menejer" in 14 values). "Yönetici" stays Administrator; "… Manager" screen names ("Content Manager", "Calendar Manager") are "… Yöneticisi" and do not name the role.
- Password: "Parola" ("Parola Değiştir", "Parolayı Belirle", "Parolamı Unuttum"); "Şifre" left in other values is an old form, replaced when met.
- Short link: "Kısa Bağlantı" (title, breadcrumb and field alike).
- Opt-In (contact consent to commercial messages): "İleti İzni"; the CAN-SPAM wording reads "ticari elektronik ileti mevzuatının tanımladığı biçimde" in Turkish, not a US law.
- ERP quotes (`erp_quotes.php`, key "Quotes"): "Fiyat Teklifleri", apart from the store's offers ("Offers": "Teklifler"). The key "Quote" stays "Alıntı": the designer and templates use it for a block quote.
- e-mail sender fields: "Gönderen Adı", "Gönderen E-posta Adresi", "Yanıt E-posta Adresi".

## What was fixed in code, not in the shards (#2052)?
- A comment's stored literal `Anonymous` (the name choice stores it untranslated) or an empty name shows through `pg_comment_display_name()` in the comment list and the dashboard Activity card.
- `view_visitor_report.php` used the Order Report's "Order Report Filters" key; `mass_edit.php` passed "Page Area" to `{var:1}` without `lang()`; the DKIM selector placeholder and the backups' date (`date('d F Y')`, now `get_absolute_time()` with `data-order`) were English; the product's private access field is "Access duration", not "Length".

## What is left?
- "From"/"To" are one key each for e-mail senders and date-range filters ("Şundan/Şuna" fits neither); date filters need their own keys, a change across many list screens.

## Where does the "Member ID" label come from?
- From the `MEMBER_ID_LABEL` setting (`config.member_id_label`), shown as stored. The Turkish starter site (`data/backups/turkish_default/sql.sql`) installs "Üye Kimliği"; a site already installed keeps its value until Security settings change it.

Adding or changing a value: `php tools/tr_add.php [--replace]` only (`lang-files.md`).
