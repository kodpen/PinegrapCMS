---
name: "pinegrap-veritabani"
description: "Pinegrap CMS'te SQL yazarken, tablo/kolon okurken veya yüksek hacimli sayaç/rapor tasarlarken kullan. db() sözleşmesi, tekil tablo adları, sessizce yanlış sonuç veren sorgu tuzakları, rollup kuralları."
---

# Pinegrap — veritabanı sözleşmesi

Şema **değiştiriyorsan** bu dosya yetmez: `pinegrap-sema-adimi` skill'ini de aç.

## Sözleşme

- `db('SQL')` çalıştırır, `db_items('SQL')` satır dizisi döner, `db_value('SQL')`
  tek değer döner. `db_items()` dizi döner — `foreach` ile gez,
  `mysqli_fetch_assoc()` çağırma.
- Değerleri `e($val)` ile escape et (`escape()` eski alias). LIKE içinde
  `e(escape_like($val))`: `escape_like()` yalnız `%` ve `_` kaçışlar, SQL
  kaçışı yapmaz — tek başına kullanmak SQL enjeksiyonudur (2026.4.7).
- **Sorgu hatası istisna fırlatmaz, `false` döner.** `init.php`, `router.php` ve
  `install/index.php` `mysqli_report(MYSQLI_REPORT_OFF)` çağırır; kod tabanı
  baştan sona bu sözleşmeye göre yazılmıştır (96 yerde
  `mysqli_query(...) or exit(...)`). **Bu satırları kaldırma** — kaldırılırsa
  PHP 8.1+'te her SQL hatası gövdesi boş bir 500 olur.
- Hatayı `mysqli_error(db::$con)` ile oku; try/catch tek emniyet değildir.
- MyISAM kullanılan tablolarda transaction yoktur; çok adımlı yazmada sırayı
  kendin güvenceye al (önce doğrula, sonra yaz). 2026.4.8'den itibaren çekirdek
  tablolar InnoDB'dir; eşik üstü büyük tablolar operatör çevirene kadar MyISAM
  kalabilir — çok adımlı yazmada yine önce doğrula sonra yaz.
- **InnoDB satır sınırı (8126 bayt / 16 KB sayfa):** ≤255 oktetlik VARCHAR
  (utf8mb4'te VARCHAR(63) ve altı) satır dışına çıkamaz, tam boyuyla sayılır;
  ≥256 oktet ve TEXT 41 bayt sayılır. Çok kolonlu tabloda kısa VARCHAR yerine
  TEXT ya da VARCHAR(64+) aç. Tahmin: `pg_innodb_row_estimate($table)`
  (`includes/fn/innodb.php`, sunucu kuralına göre); dönüşüm sığmayan tabloya
  `too_wide` der. Bakım (OPTIMIZE / CHECK / REPAIR, katalog, sunucu bilgisi)
  `includes/fn/db_maintenance.php`, ekranı `database_engine.php`.

## Tablo adları tekildir

`page`, `style`, `pregion`, `cregion`, `dregion`, `forms`, `form_data`.
`pages` diye bir tablo yok — sorgu patlar.

## Sessizce yanlış sonuç veren tuzaklar

- `INT` kolonu `''` ile karşılaştırma: MySQL `''`'i 0'a çevirir ve **hiçbir
  kayda ait olmayan tüm satırlar** eşleşir. `LEFT JOIN` ile gelen id'yi
  sorguya koymadan önce boş mu diye bak.
- `DATE` kolonunu `''` ile karşılaştırma; `!= '0000-00-00'` kullan (strict mode
  sorguyu sessizce düşürür).
- Ürün formu okuyan her sorguda `form_type = 'product'` pinle — şablon
  satırlarının `page_id`/`product_id` değeri 0'dır.
- `form_data.form_id → forms.id` bağlıdır; `form_data.id` ≠ `forms.id`.
- `products_images_xref` tablosunda **`id` kolonu yoktur** — `ORDER BY id`
  ölümcül hata verir.
- `product_groups_attributes_xref`'in tekil anahtarı yoktur; aynı nitelik gruba
  iki kez bağlanabilir, grup başına niteliği bir kez oku.
- MySQL `SET` soldan sağa değerlendirilir ve sonraki atama önceki kolonun
  **yeni** değerini görür — atama sırasını değiştirme.
- `SELECT *` kullanan yeri kolon adı yazarak "iyileştirme": bazı yerler
  (OG görsel çözücüleri, `pg_pb_copy_row()`) sonradan gelen kolonlara bağlıdır;
  elle tutulan kolon listesi bir sonraki kolonu sessizce kopyalamaz.
- Migration'ın eklediği tablo/kolonu okuyan kod önce yoklasın
  (`pg_schema_has($table, $column)`; `waf_table_has_column()` ve `SHOW TABLES LIKE` eski yoldur) ve bir "hazır mı" kapısı yazsın
  (`pg_page_noindex_ready()` deseni); birden çok `ALTER` varsa **tüm** kolonları
  birden yokla.
- Yeni IP kolonu `VARCHAR(45)` olur (IPv4-mapped IPv6'nın en uzun hâli);
  `VARCHAR(15)` IPv6'yı sessizce keser ve yasak hiç uygulanmaz.
- IP yasağı yazan kod tek atomik `INSERT ... ON DUPLICATE KEY UPDATE` olsun;
  SELECT-sonra-INSERT yarış taşır.

## Sayfa / tasarımcı tarafı

- Tasarımcı ağacını okuyan sorgu `pg_page_tree_sql_expr()` kullansın
  (`COALESCE(NULLIF(page.page_tree_json,''), style.style_tree_json)`); tek sayfa
  için `pg_page_tree_json($page_id)`. `style.style_tree_json`'a doğrudan LIKE yazma.
- Render `page.page_tree_code`'u okur, `page_tree_json`'ı değil.
- "Görsel tasarımcı sayfası" ölçütü `layout_type='system'` **değildir** —
  `pg_page_is_visual_design()`; düzenleme bağlantısı `pg_page_edit_url()`.
- Tasarımın sayfalarını okuyan her sorguya `pg_designer_not_binned_sql()` ekle
  (yoksa Geri Dönüşüm Kutusundaki sayfa sekmede çıkar).
- `page_folder` asla 0 yazılmaz (0 olan sayfa Dosya Yöneticisinde görünmez).
- `sitemap=1` ile `noindex=1` birlikte olamaz; `sitemap` bayrağını okuyan her
  yeni sorgu `noindex`i de sorsun.
- `files.name` adresin kendisidir (`FILE_DIRECTORY_PATH . '/' . name`); adı
  değiştiren kod diskteki dosyayı da `rename()` etmeli ve `rename()`
  başarısızsa `UPDATE` hiç çalışmamalı.

## E-ticaret tarafı

- `products`/`product_groups` yalnız `ECOMMERCE === true` iken okunur; kontrol
  kalıbı `defined('ECOMMERCE') && ECOMMERCE === true`.
- **Fiyatlar kuruş cinsinden `int`.** Kesirli kuruşta `(int)` ile kırpma,
  `round()` kullan.
- Varyant için ayrı tablo yoktur: `products`'taki her satır satın alınabilir
  tek şeydir.
- `products.tax_rate` DECIMAL(6,3) UNSIGNED **NULL**: `NULL` = bölgenin oranı,
  `0.000` = sıfır oranlı. Oranı `get_effective_tax_rate()` ile çöz, girdiyi
  `parse_tax_rate()`, çıktıyı `format_tax_rate()` ile işle.
- Vergi satır toplamı üzerinden: `order_items.tax_total`; toplarken
  `SUM(tax_total)` yaz, adetle **çarpma**. `order_items.tax` artık yazılmıyor.
- `orders` yazarken: `reference_code` UNIQUE
  (`generate_order_reference_code()` şart), `payment_method` bir ENUM (aralık
  dışı değer sessizce `''` olur), `order_date` boşsa sipariş listede ve raporda
  **hiç görünmez**.
- Sipariş numarası `LOCK TABLES next_order_number WRITE` ile alınır; kilit
  bağlantının tamamına uygulanır — o fonksiyonun içine başka sorgu ekleme.
- ERP ekranlarında ürün adı `products.short_description`; **`products.name`
  stok kodudur** (`COALESCE(NULLIF(short_description,''),name)`).

## Yüksek hacimli sayaç ve rollup

- Raporu ham `visitors`/`waf_log` üzerinden sayma; özet tablolarını kullan
  (`visitor_stats_hourly`, `visitor_content_hourly`).
- Bileşik benzersiz anahtarı `sha1(...)` olarak tut — düz bileşik UNIQUE
  utf8mb4'te 767 bayt sınırını aşar. Sorgu dizesini anahtara koyma (fuzzing'de
  toplama ölür).
- Satır tavanını `MAX(id)` ile hesapla, `COUNT(*)` ile değil.
- Toplamı SQL'de al (`GROUP BY` + `SUM()`); detayı ayrı sorguda `LIMIT/OFFSET`
  ile çek, tam veri akışı CSV olsun (`MYSQLI_USE_RESULT`).
- Raporda `SUM(hit_count)` kullan, `COUNT(*)` değil — aksi hâlde toplanmış
  kayıtlar avuç içi kadar görünür.
- ONLY_FULL_GROUP_BY'a uy: `GROUP BY 1,2` yazma, gruplanmamış kolon seçme,
  `SHA1()`'i gruplamadan sonra hesapla.
- Gün bazlı imleçte `strtotime('+1 day', $ts)` kullan, `+86400` değil (DST).
- Rollup/backfill canlıyken kaynak tabloyu süpürme; bitince `DROP` değil
  `RENAME TABLE ... TO <tablo>_old`.
- p95'i `OFFSET` ile hesaplama; 20.000 satır üstünde en yavaş %5'i okuyup
  minimumunu al.
- İndeks yazma maliyetidir: rapor sorgusunun kullanmadığı indeksi tutma.
- Özet tablolar türetilmiş veridir — bozulursa yerinde düzeltme, `TRUNCATE` +
  kaynaktan yeniden kur.

## Bitirmeden önce

`php tools/lint.php` temiz olmalı. Şema değiştiysen `pinegrap-sema-adimi`
kontrol listesini de uygula.
