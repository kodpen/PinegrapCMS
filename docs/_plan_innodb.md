# Plan 2 — Veritabanı: InnoDB geçişi ve DB katmanı (ana ajan Fable 5.1, alt ajanlar Opus 5.5)

Durum: plan (2026-10-08). Kaynak keşif: `docs/_tespit_2026_10_08/refactor_altyapi_raporu.md` maddeler 9, 10, 11, 12, 21.
Satır numaraları `development` @ `0294c42`'ye göre; kaymışsa grep ile bul, yeniden keşif yapma.

## 0. Sabit bağlam

- Depo kökü `/workspace/pinegrapcms`, ürün kodu `pinegrap/`. Önce `CLAUDE.md`; skill'ler (yolları alt ajan görevine yaz): `.claude/skills/pinegrap-veritabani/SKILL.md`, `pinegrap-sema-adimi`, `pinegrap-verilmis-kararlar`, `pinegrap-erp` (MyISAM kısıtları bölümü), `pinegrap-yeni-dosya`.
- Dal `development`'tan, PR tabanı `development`, AI izi yok.
- Ortak dosyalar (`includes/migrations/2026.4.8.php`, `init.php`, `changelog.txt`, `tr.json`, `docs/degisiklikler.md`, `docs/CLAUDE-tam.md`): yazmadan önce `git fetch origin development && git merge origin/development`; `upgrade_to_2026_4_8()` gövdesine **yalnız kendi satırlarını** ekle; push öncesi rebase.
- Şema etiketleri bu plan için **8.10–8.19** (öneri; Erdal onaylar). Fonksiyon adı `upgrade_2026_4_8_<konu>()`.
- Test koşucusu `php tools/test.php`; sandbox `bash tools/setup_sandbox.sh` (bu planda **zorunlu**: her migration iki kez koşar, ikincisi "skipped").
- Bitiş kriteri: lint, check_lang, test.php temiz; migration idempotent; yorumlar İngilizce; PR'da "doğrulayamadıklarım".
- **Verilmiş kararlar:** `data/backups/` dökümlerine **dokunulmaz** (dönüşüm yalnız migration'la); `mysqli_report(MYSQLI_REPORT_OFF)` kalır; sorgu hatası `false` döner, istisna fırlatmaz; `user.secret_key*` kolonları durur; MySQL 5.7 desteklenir.
- **Paralel planlar — dokunma:** `email_campaign_job.php` `LOCK TABLES` satırları (Plan 4 kaldırır/değiştirir; sen yalnız motoru çevir), `api.php` (Plan 1), `update_order.php`/`view_orders.php`/kargo (Plan 3), `includes/fn/auth.php`/giriş dosyaları (Plan 5). `SHOW COLUMNS` yoklamalarını **yalnız** sahip olmadığın dosyalarda bırak; yardımcıyı ekle ve duyur.

## 1. Kapsam ve dosya sahipliği

| Faz | İş | Etiket | Dosyalar |
|---|---|---|---|
| 0 | Envanter + altın kayıt | — | geçici betikler (PR'a girmez) |
| 1 | Sipariş grubu InnoDB | 8.10 | `2026.4.8.php` |
| 2 | Ürün grubu | 8.11 | `2026.4.8.php` |
| 3 | Kişi/log/kampanya grubu | 8.12 | `2026.4.8.php` |
| 4 | FULLTEXT'li tablolar | 8.13 | `2026.4.8.php` |
| 5 | `pg_db_run()` çekirdeği + sorgu sayacı | 8.14 (`perf_stats.query_count`) | `includes/fn/core.php:212-365`, `init.php` perf bloğu (yalnız ekleme), `includes/migrations/2026.3.2.php` okunur |
| 6 | `pg_schema_has()` | — | `includes/fn/core.php` (yeni fonksiyon), `purge_cache.php`/`pg_purge_caches()` (önbellek dosyasını siler) |
| 7 (opsiyonel) | `config_kv` | 8.15 | `init.php` (yalnız ekleme), `includes/fn/core.php` |

## 2. Adımlar

### Faz 0 — Envanter ve altın kayıt (önce, mutlaka)
- **Yap (devredilebilir, tek alt ajan):**
  1. `data/backups/english_default/sql.sql` ve `turkish_default/sql.sql` içindeki `CREATE TABLE` → tablo, motor, FULLTEXT indeks, **AUTO_INCREMENT kolonu PRIMARY KEY'in ilk kolonu mu** (MyISAM bileşik anahtarda ikinci kolon AUTO_INCREMENT'e izin verir, **InnoDB vermez** — ALTER başarısız olur), `MAX_ROWS`/`AVG_ROW_LENGTH` gibi MyISAM'a özgü seçenekler. Tablo: `docs/_innodb_envanter.md`.
  2. Kod tarafı: `grep -rn "MATCH\s*(" pinegrap --include=*.php` (12 sorgu bekleniyor) → dosya:satır, tablo, aranan kolonlar; `grep -rn "LOCK TABLES\|REPAIR TABLE\|OPTIMIZE TABLE\|CHECK TABLE\|FLUSH TABLES\|myisam" -i` → dosya:satır (14 LOCK bekleniyor; `optimize.php`, `mysqldump.php`, `install_heavy_tables()` `runner.php:1313` ne yapıyor).
  3. `install_set_engine()` (`runner.php:556`) ve `install_table_engine()` okunur; büyük tabloların ilerleme/zaman aşımı davranışı (`install_heavy_tables()`) not edilir.
- **Altın kayıt (sandbox):** veri tohumla (ürün 200+, kişi 200+, birkaç sipariş; 3 harfli ve stopword içeren arama terimleri dahil). 12 `MATCH … AGAINST` sorgusunu `/tmp/golden/ft_<n>.json`'a yaz (tam SQL + sonuç id listesi). `SHOW TABLE STATUS` → `/tmp/golden/engines_before.txt`.
- **Kanıt:** envanter dosyası; "InnoDB'ye geçemeyecek" tablo listesi (varsa) ve her biri için düzeltme (PK yeniden tanımı ayrı alt adım).

### Faz 1–3 — Motor dönüşümü (B; grup başına bir alt adım)
- **Yap:** `upgrade_2026_4_8_innodb_orders()` (8.10): `next_order_number`, `orders`, `order_items` ve `order_id` taşıyan tüm çocuk tablolar (envanterden), `ship_tos`, `shipping_tracking_numbers`. `upgrade_2026_4_8_innodb_products()` (8.11): `products`, `product_groups`, `product_*`, `inventory*`. `upgrade_2026_4_8_innodb_people()` (8.12): `contacts`, `log`, `email_recipients`, `email_campaigns`, `config`, `forms`/`form_data` (envantere göre). Her tablo `install_set_engine($table, 'InnoDB')`; önce PK düzeltmesi gerekenler için `install_add_index`/uygun yardımcı; `install_note()` her alt adımda operatöre süre uyarısı ("büyük tablolar yeniden yazılır").
- Ağır tablolar `install_heavy_tables()` listesine (varsa) girer; `max_execution_time` davranışı runner'da nasıl, oku.
- **Dokunma:** `LOCK TABLES` satırları (InnoDB'de çalışmaya devam eder; Plan 4 kaldırır). `erp_stock_apply_pending()` kalır (ERP skill kararı; ayrı tur).
- **Kanıt:** sandbox'ta migration **iki kez** (`config.version` → `2026.4.7` çekilerek), ikincisinde tüm adımlar "already InnoDB, skipped"; `SHOW TABLE STATUS` → `/tmp/golden/engines_after.txt` (hedef tablolar InnoDB); uçtan uca: mağazada sipariş ver (`submit_order.php`), tezgâh satışı (`add_order.php`), ERP fatura kes + tahsilat, kampanya işi (`email_campaign_job.php`) hata vermeden koşar; `php tools/test.php`; `tests/innodb_test.php`: envanterdeki "dönüştürülecek" listesi ile migration'daki liste birebir (listeyi migration'dan `pg_innodb_core_tables()` gibi saf bir fonksiyonla döndür ki test okuyabilsin).
- **Devredilebilir:** envanter ve altın kayıt evet; migration gövdesi ana ajanda (ortak dosya, skill kontrol listesi).

### Faz 4 — FULLTEXT'li tablolar (8.13)
- **Yap:** envanterdeki 4 tablo son sırada. InnoDB FULLTEXT: `innodb_ft_min_token_size` (varsayılan 3; MyISAM `ft_min_word_len` 4) ve stopword listesi farklı; MySQL 5.7'de destekleniyor.
- **Kanıt:** 12 MATCH sorgusu golden ile karşılaştırılır; **farklar kabul edilebilir** yalnız "daha çok sonuç (3 harfli kelimeler artık bulunuyor)" yönündeyse; eksilen sonuç varsa sebebi (stopword) PR'a yazılır ve gerekiyorsa sorgu `IN BOOLEAN MODE` ile uyarlanır. `OPTIMIZE TABLE` çağrıları InnoDB'de "Table does not support optimize, doing recreate + analyze" uyarısı verir — `optimize.php` bunu hata saymamalı (kontrol et).

### Faz 5 — `pg_db_run()` çekirdeği ve sorgu sayacı (K)
- **Yap:** `db()`, `db_value()`, `db_values()`, `db_item()`, `db_items()` (`core.php:212-365`) aynı hata bloğunu 5 kez tekrarlıyor → tek `pg_db_run($sql)` (bağlantı kontrolü, `@mysqli_query`, `install_query_failed`, `output_error`); dönüş sözleşmeleri **değişmez** (`false` döner, istisna yok). Statik sayaç + toplam süre (`pg_db_stats()`); `init.php` perf kapanışında `perf_stats.query_count` yazılır (8.14 `install_add_column`); `view_performance_log.php` sütunu gösterir.
- **Kanıt:** `tests/db_core_test.php`: bağlantı yokken `db()` `false` döner ve `pg_db_stats()['count']` artmaz (DB'siz koşucuda doğrulanabilir); sandbox'ta performans günlüğünde sorgu sayısı dolu; migration iki kez.

### Faz 6 — `pg_schema_has($table, $column = null)` (O)
- **Yap:** `information_schema` ile **tam eşitlik** (`WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?`; `LIKE` yok); sonuç `data/temp/schema_<config.version>_<VERSION>.json`; `pg_purge_caches()` ve yükseltme sonu dosyayı siler. Ham `mysqli_query` değil, `db_value()`; `escape()` ile.
- Yalnız sahip olduğun dosyalardaki yoklamaları çevir (`includes/fn/core.php`, `includes/fn/output.php`, `includes/fn/system_status.php`); diğer planların dosyalarına dokunma, `docs/CLAUDE-tam.md`'ye "yeni kodda `pg_schema_has()` kullanılır" notu ekle.
- **Kanıt:** `tests/schema_has_test.php` (önbellek dosyası formatı: yaz/oku, sürüm değişince anahtar değişir); sandbox'ta `SHOW COLUMNS` yerine geçen yerler aynı sonucu veriyor; `includes/migrations` 0555 senaryosu: `config.version` ileri ama şema geri → önbellek anahtarı iki değeri de içerdiği için bayat sonuç yok (elle dene).

### Faz 7 (opsiyonel) — `config_kv`
- **Yap:** `config_kv(name VARCHAR(100) PK, value TEXT, updated_at INT)` (8.15); `init.php` `SELECT * FROM config`'in ardına tek `SELECT name, value FROM config_kv` ve `define()`; `pg_setting_set($name, $value)`. Mevcut kolonlar taşınmaz.
- **Kanıt:** migration iki kez; `tests/config_kv_test.php` (ad doğrulama: `[a-z0-9_]{1,100}`); sandbox'ta bir ayar yaz-oku.

## 3. Varsayılan kararlar
- FULLTEXT tabloları en son ve ayrı alt adım; eksilen arama sonucu PR'da açıklanmadan merge edilmez.
- `LOCK TABLES`, `erp_stock_apply_pending()` bu planda kaldırılmaz.
- Döküm dosyaları değişmez; yeni kurulum MyISAM ile kurulur, ilk yükseltmede döner (tutarlı: tek yol).
- `pg_db_run()` dönüş ve hata sözleşmesi aynen.

## 4. Elle doğrulanacaklar (Erdal)
- Büyük canlı tabloda ALTER süresi (dev'de gerçek veri kopyasıyla ölç; `install_heavy_tables()` uyarısı yeterli mi); barındırıcıda `innodb_buffer_pool_size` küçükse performans; yedek/geri yükleme (`mysqldump.php`) InnoDB dökümüyle sorunsuz.

## 5. PR açıklaması şablonu
Ne değişti · Etiketler (8.10–8.1x) · Envanter dosyası · Golden karşılaştırma (engines, 12 FT sorgu: aynı/fark ve gerekçe) · Uçtan uca senaryolar · Denetim çıktıları · **Doğrulayamadıklarım** · Sorular.
